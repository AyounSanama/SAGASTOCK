import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';
import '../../../core/network/api_client.dart';

class OrderService {
  OrderService({ApiClient? client}) : _client = client ?? ApiClient();
  final ApiClient _client;
  final _storage = const FlutterSecureStorage();
  static const _outboxKey = 'offline_order_outbox';
  Future<Options> _auth() async => Options(headers: {'Authorization':'Bearer ${await _storage.read(key:'auth_token')}'});
  Future<List<Map<String,dynamic>>> organizations()=>_list('/organizations','offline_organizations');
  Future<List<Map<String,dynamic>>> orders(String id) async { await sync(); return _list('/organizations/$id/orders','offline_orders_$id'); }
  Future<Map<String,dynamic>> options(String id) async { final key='offline_order_options_$id'; try { final r=await _client.dio.get<Map<String,dynamic>>('/organizations/$id/orders/options',options:await _auth()); final data=r.data??{}; await _storage.write(key:key,value:jsonEncode(data)); return data; } on DioException catch(e){if(e.response!=null) rethrow;final cache=await _storage.read(key:key);return cache==null?{}:(jsonDecode(cache) as Map).cast<String,dynamic>();} }
  Future<bool> create(String id,Map<String,dynamic> data){data['offline_uuid']??=const Uuid().v4();return _send('post','/organizations/$id/orders',data);}
  Future<bool> submit(String id,String order)=>_send('post','/organizations/$id/orders/$order/submit',{});
  Future<bool> decide(String id,String order,String decision,{String? comment})=>_send('post','/organizations/$id/orders/$order/decision',{'decision':decision,'comment':comment});
  Future<bool> prepare(String id,String order,List<Map<String,dynamic>> lines,{bool complete=false})=>_send('post','/organizations/$id/orders/$order/prepare',{'lines':lines,'complete':complete});
  Future<int> pendingCount() async=>(await _outbox()).length;
  Future<bool> _send(String method,String path,Map<String,dynamic> data) async {try{await _client.dio.request(path,data:data,options:(await _auth()).copyWith(method:method.toUpperCase()));return true;}on DioException catch(e){if(e.response!=null)rethrow;final queue=await _outbox();queue.add({'operation_id':const Uuid().v4(),'method':method,'path':path,'data':data,'queued_at':DateTime.now().toIso8601String(),'attempts':0});await _storage.write(key:_outboxKey,value:jsonEncode(queue));return false;}}
  Future<void> sync() async {final queue=await _outbox();final remaining=<Map<String,dynamic>>[];for(final item in queue){try{await _client.dio.request(item['path'],data:item['data'],options:(await _auth()).copyWith(method:'${item['method']}'.toUpperCase()));}on DioException catch(e){remaining.add({...item,'attempts':((item['attempts'] as num?)?.toInt()??0)+1,'last_error':e.response?.data?.toString()??e.message});}}await _storage.write(key:_outboxKey,value:jsonEncode(remaining));}
  Future<List<Map<String,dynamic>>> _list(String path,String key) async {try{final r=await _client.dio.get<Map<String,dynamic>>(path,options:await _auth());final data=(r.data?['data'] as List? ?? []).cast<Map<String,dynamic>>();await _storage.write(key:key,value:jsonEncode(data));return data;}on DioException catch(e){if(e.response!=null)rethrow;final cache=await _storage.read(key:key);return cache==null?[]:(jsonDecode(cache) as List).cast<Map<String,dynamic>>();}}
  Future<List<Map<String,dynamic>>> _outbox() async {final value=await _storage.read(key:_outboxKey);return (value==null?[]:jsonDecode(value) as List).cast<Map<String,dynamic>>();}
}
