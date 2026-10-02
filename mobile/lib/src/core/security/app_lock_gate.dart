import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/data/auth_service.dart';
import '../sync/sync_service.dart';
import '../theme/app_tokens.dart';
import 'app_lock.dart';

/// Enveloppe toute l'application : verrouillage par PIN (S-03) et demande de
/// reconnexion quand le serveur refuse le jeton (opérations conservées).
class AppLockGate extends StatefulWidget {
  const AppLockGate({required this.router, required this.child, super.key});

  final GoRouter router;
  final Widget child;

  @override
  State<AppLockGate> createState() => _AppLockGateState();
}

class _AppLockGateState extends State<AppLockGate> with WidgetsBindingObserver {
  final AppLock _lock = AppLock.instance;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    widget.router.routerDelegate.addListener(_evaluate);
    SyncService.authenticationRequired.addListener(_onAuthenticationRequired);
    _timer = Timer.periodic(const Duration(seconds: 15), (_) => _evaluate());
    _evaluate();
  }

  @override
  void dispose() {
    _timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    widget.router.routerDelegate.removeListener(_evaluate);
    SyncService.authenticationRequired.removeListener(
      _onAuthenticationRequired,
    );
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _evaluate();
  }

  Future<void> _evaluate() async {
    await _lock.evaluate(hasSession: await AuthService().hasSession());
  }

  Future<void> _onAuthenticationRequired() async {
    if (!SyncService.authenticationRequired.value || !mounted) return;
    SyncService.authenticationRequired.value = false;
    // Session fermée localement ; la file d'attente reste dans la base.
    await AuthService().clearAuthenticatedSession();
    await _evaluate();
    if (!mounted) return;
    widget.router.go('/login');
    ScaffoldMessenger.maybeOf(context)?.showSnackBar(
      const SnackBar(
        content: Text(
          'Session expirée : reconnectez-vous. Vos opérations en attente sont conservées et seront envoyées.',
        ),
        duration: Duration(seconds: 8),
      ),
    );
  }

  Future<void> _lockedOut() async {
    await AuthService().clearAuthenticatedSession();
    await _evaluate();
    if (mounted) widget.router.go('/login');
  }

  @override
  Widget build(BuildContext context) {
    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: (_) => _lock.touch(),
      child: ValueListenableBuilder<AppLockState>(
        valueListenable: _lock.state,
        builder: (context, state, _) => Stack(
          children: [
            widget.child,
            if (state != AppLockState.unlocked)
              // Overlay propre : l'écran est au-dessus du Navigator.
              Positioned.fill(
                child: Overlay(
                  initialEntries: [
                    OverlayEntry(
                      builder: (_) => PinScreen(
                        key: ValueKey(state),
                        setup: state == AppLockState.setupRequired,
                        lock: _lock,
                        onLockedOut: _lockedOut,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Écran de création (deux saisies) ou de déverrouillage du PIN.
class PinScreen extends StatefulWidget {
  const PinScreen({
    required this.setup,
    required this.lock,
    required this.onLockedOut,
    super.key,
  });

  final bool setup;
  final AppLock lock;
  final Future<void> Function() onLockedOut;

  @override
  State<PinScreen> createState() => _PinScreenState();
}

class _PinScreenState extends State<PinScreen> {
  final _controller = TextEditingController();
  String? _first;
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final pin = _controller.text.trim();
    if (!AppLock.isValidPin(pin)) {
      setState(() => _error = 'Le code PIN doit comporter 4 à 6 chiffres.');
      return;
    }
    setState(() => _busy = true);
    try {
      if (widget.setup) {
        if (_first == null) {
          _first = pin;
          _controller.clear();
          setState(() => _error = null);
          return;
        }
        if (_first != pin) {
          _first = null;
          _controller.clear();
          setState(
            () => _error = 'Les deux codes sont différents. Recommencez.',
          );
          return;
        }
        await widget.lock.setPin(pin);
        return;
      }
      if (await widget.lock.unlock(pin)) return;
      _controller.clear();
      if (widget.lock.lockedOut) {
        await widget.onLockedOut();
        return;
      }
      setState(
        () => _error =
            'Code incorrect. ${widget.lock.remainingAttempts} essai(s) restant(s) avant déconnexion.',
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final title = widget.setup
        ? (_first == null ? 'Créez votre code PIN' : 'Confirmez votre code PIN')
        : 'Application verrouillée';
    final subtitle = widget.setup
        ? 'Il protège l’application après 5 minutes d’inactivité (4 à 6 chiffres).'
        : 'Saisissez votre code PIN pour continuer.';
    return Material(
      color: AppColors.background,
      child: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 360),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(
                    Icons.lock_outline,
                    size: 48,
                    color: AppColors.primaryStrong,
                  ),
                  const SizedBox(height: 16),
                  Text(
                    title,
                    style: Theme.of(context).textTheme.titleLarge,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 8),
                  Text(subtitle, textAlign: TextAlign.center),
                  const SizedBox(height: 24),
                  TextField(
                    key: const Key('pin-field'),
                    controller: _controller,
                    autofocus: true,
                    obscureText: true,
                    keyboardType: TextInputType.number,
                    maxLength: 6,
                    textAlign: TextAlign.center,
                    decoration: InputDecoration(
                      labelText: 'Code PIN',
                      errorText: _error,
                      counterText: '',
                    ),
                    onSubmitted: (_) => _submit(),
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: _busy ? null : _submit,
                      child: Text(
                        widget.setup
                            ? (_first == null ? 'Continuer' : 'Enregistrer')
                            : 'Déverrouiller',
                      ),
                    ),
                  ),
                  if (!widget.setup) ...[
                    const SizedBox(height: 8),
                    TextButton(
                      onPressed: _busy ? null : widget.onLockedOut,
                      child: const Text('Code oublié : se reconnecter'),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
