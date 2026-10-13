import 'package:flutter/material.dart';
import '../../theme.dart';
import 'auth_pattern_background.dart';

class AuthScaffold extends StatelessWidget {
  const AuthScaffold({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.step,
    required this.steps,
    required this.child,
    this.scope = 'Espace enseignant',
    this.onBack,
    this.footer,
    this.contextLogoUrl,
  });
  final String title, subtitle, scope;
  final IconData icon;
  final int step, steps;
  final Widget child;
  final VoidCallback? onBack;
  final Widget? footer;
  final String? contextLogoUrl;

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.white,
    body: Stack(
      fit: StackFit.expand,
      children: [
        const Positioned.fill(child: AuthPatternBackground()),
        SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) => SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              child: Center(
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    maxWidth: 480,
                    minHeight: constraints.maxHeight,
                  ),
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(24, 16, 24, 20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Row(
                              children: [
                                Image.asset(
                                  'assets/logo.png',
                                  width: 40,
                                  height: 40,
                                ),
                                const SizedBox(width: 10),
                                const Expanded(
                                  child: Text(
                                    'Auditron-X',
                                    style: TextStyle(
                                      fontFamily: AuditronFonts.display,
                                      fontSize: 18,
                                      fontWeight: FontWeight.w700,
                                      color: AuditronColors.ink900,
                                    ),
                                  ),
                                ),
                                SizedBox(
                                  width: 48,
                                  height: 48,
                                  child: onBack == null
                                      ? null
                                      : IconButton(
                                          tooltip: 'Retour',
                                          onPressed: onBack,
                                          icon: const Icon(
                                            Icons.arrow_back_rounded,
                                          ),
                                        ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 30),
                            Semantics(
                              label: 'Etape ${step + 1} sur $steps',
                              child: Row(
                                children: List.generate(
                                  steps,
                                  (index) => Expanded(
                                    child: Padding(
                                      padding: EdgeInsets.only(
                                        right: index == steps - 1 ? 0 : 6,
                                      ),
                                      child: Container(
                                        height: 4,
                                        decoration: BoxDecoration(
                                          color: index <= step
                                              ? AuditronColors.brand700
                                              : AuditronColors.ink100,
                                          borderRadius: BorderRadius.circular(
                                            2,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(height: 36),
                            Align(
                              alignment: Alignment.centerLeft,
                              child: Container(
                                width: 64,
                                height: 64,
                                decoration: BoxDecoration(
                                  color: AuditronColors.brand50,
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Icon(
                                  icon,
                                  size: 30,
                                  color: AuditronColors.brand700,
                                ),
                              ),
                            ),
                            const SizedBox(height: 24),
                            Text(
                              scope,
                              style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: AuditronColors.brand700,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              title,
                              style: const TextStyle(
                                fontFamily: AuditronFonts.display,
                                fontSize: 26,
                                height: 1.25,
                                fontWeight: FontWeight.w700,
                                color: AuditronColors.ink900,
                              ),
                            ),
                            const SizedBox(height: 12),
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                if (contextLogoUrl != null) ...[
                                  Image.network(
                                    contextLogoUrl!,
                                    width: 32,
                                    height: 32,
                                    fit: BoxFit.contain,
                                    errorBuilder: (_, error, stack) =>
                                        const Icon(
                                          Icons.apartment_outlined,
                                          size: 32,
                                          color: AuditronColors.brand700,
                                        ),
                                  ),
                                  const SizedBox(width: 10),
                                ],
                                Expanded(
                                  child: Text(
                                    subtitle,
                                    style: const TextStyle(
                                      fontSize: 14,
                                      height: 1.5,
                                      color: AuditronColors.ink500,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 30),
                            child,
                          ],
                        ),
                        Padding(
                          padding: const EdgeInsets.only(top: 36),
                          child:
                              footer ??
                              const Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    Icons.lock_outline_rounded,
                                    size: 14,
                                    color: AuditronColors.ink500,
                                  ),
                                  SizedBox(width: 6),
                                  Flexible(
                                    child: Text(
                                      'Connexion sécurisée',
                                      style: TextStyle(
                                        fontSize: 12,
                                        color: AuditronColors.ink500,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ],
    ),
  );
}

class AuthError extends StatelessWidget {
  const AuthError(this.message, {super.key});
  final String message;
  @override
  Widget build(BuildContext context) => Semantics(
    liveRegion: true,
    child: Padding(
      padding: const EdgeInsets.only(top: 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.error_outline,
            size: 20,
            color: Theme.of(context).colorScheme.error,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                color: Theme.of(context).colorScheme.error,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
