import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

AppBar buildAuditronAppBar(
  BuildContext context, {
  required String title,
  required String subtitle,
  List<Widget>? actions,
  bool automaticallyImplyLeading = true,
}) {
  final scaler = MediaQuery.textScalerOf(context);
  const titleStyle = TextStyle(
    fontFamily: 'Lato',
    fontSize: 22,
    fontWeight: FontWeight.w700,
    color: Colors.white,
    height: 1.25,
  );
  const subtitleStyle = TextStyle(
    fontFamily: 'Lato',
    fontSize: 12,
    color: Colors.white70,
    height: 1.4,
  );
  final availableWidth = MediaQuery.sizeOf(context).width - 32;
  // La hauteur suit le retour a la ligne des titres et le texte agrandi.
  final titlePainter = TextPainter(
    text: TextSpan(text: title, style: titleStyle),
    textDirection: Directionality.of(context),
    textScaler: scaler,
    maxLines: 2,
  )..layout(maxWidth: availableWidth);
  final subtitlePainter = TextPainter(
    text: TextSpan(text: subtitle, style: subtitleStyle),
    textDirection: Directionality.of(context),
    textScaler: scaler,
    maxLines: 2,
  )..layout(maxWidth: availableWidth);
  final headingHeight = titlePainter.height + subtitlePainter.height + 20;
  titlePainter.dispose();
  subtitlePainter.dispose();
  return AppBar(
    automaticallyImplyLeading: automaticallyImplyLeading,
    systemOverlayStyle: SystemUiOverlayStyle.light,
    title: Row(
      children: [
        Image.asset('assets/logo.png', width: 28, height: 28),
        const SizedBox(width: 8),
        const Flexible(
          child: Text(
            'Auditron-X',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    ),
    actions: actions,
    bottom: PreferredSize(
      preferredSize: Size.fromHeight(headingHeight),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        child: SizedBox(
          width: double.infinity,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: titleStyle,
              ),
              const SizedBox(height: 4),
              Text(
                subtitle,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: subtitleStyle,
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
