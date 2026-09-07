import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_theme.dart';
import '../data/notification_service.dart';

class NotificationsPage extends StatefulWidget {
  const NotificationsPage({super.key});
  @override
  State<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends State<NotificationsPage> {
  final _service = NotificationService();
  List<Map<String, dynamic>> _items = [];
  bool _loading = true, _offline = false;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) setState(() => _loading = true);
    try {
      _items = await _service.list();
      _offline = false;
    } on DioException catch (error) {
      if (error.response != null) rethrow;
      _items = await _service.localList();
      _offline = true;
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _read(Map<String, dynamic> item) async {
    if (item['read'] != true) {
      final online = await _service.markRead('${item['id']}');
      if (!mounted) return;
      setState(() => item['read'] = true);
      if (!online) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Lecture enregistrée hors connexion.')),
        );
      }
    }
    final path = '${item['action_path'] ?? ''}';
    if (path.startsWith('/') && mounted) context.push(path);
  }

  Future<void> _readAll() async {
    await _service.markAllRead();
    if (!mounted) return;
    setState(() {
      for (final item in _items) {
        item['read'] = true;
      }
    });
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Notifications'),
      actions: [
        TextButton(
          onPressed: _items.any((e) => e['read'] != true) ? _readAll : null,
          child: const Text('Tout lire'),
        ),
      ],
    ),
    body: RefreshIndicator(
      onRefresh: _load,
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_offline)
                  const ListTile(
                    leading: Icon(Icons.cloud_off_rounded),
                    title: Text('Notifications locales'),
                    subtitle: Text(
                      'La lecture sera synchronisée au retour du réseau.',
                    ),
                  ),
                if (_items.isEmpty)
                  const Padding(
                    padding: EdgeInsets.only(top: 80),
                    child: Column(
                      children: [
                        Icon(
                          Icons.notifications_none_rounded,
                          size: 48,
                          color: AppTheme.muted,
                        ),
                        SizedBox(height: 12),
                        Text('Aucune notification'),
                      ],
                    ),
                  ),
                for (final item in _items)
                  Card(
                    color: item['read'] == true
                        ? null
                        : AppTheme.orange.withValues(alpha: .06),
                    child: ListTile(
                      leading: Icon(
                        item['read'] == true
                            ? Icons.notifications_none
                            : Icons.notifications_active_outlined,
                        color: AppTheme.orange,
                      ),
                      title: Text(
                        '${item['title'] ?? 'Notification'}',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      subtitle: Text('${item['message'] ?? ''}'),
                      trailing: '${item['action_path'] ?? ''}'.startsWith('/')
                          ? const Icon(Icons.chevron_right_rounded)
                          : null,
                      onTap: () => _read(item),
                    ),
                  ),
              ],
            ),
    ),
  );
}
