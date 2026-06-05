import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../core/config/app_config.dart';

/// CMS 图片组件（3 级降级）
/// 1. 优先 HeroSMS CDN: `https://cdn.hero-sms.com/assets/img/{kind}/{id}[0].{ext}`
/// 2. 失败回退: 后端本地副本 `/pic/{kind}/{id}[0].{ext}`
/// 3. 还失败: 渐变背景 + 文本占位
class CmsImage extends StatelessWidget {
  /// 服务时 = service / 国家时 = country
  final String kind;
  /// hero 服务 id (go/...) 或 hero 国家 id (0/1/2/...)
  /// 末尾会加 0 拼接; country 拼接 .svg, service 拼接 0.webp
  final String? heroId;

  /// 兜底: 如果 heroId 为空, 直接使用 fallback
  final String? fallbackUrl;
  final String fallbackText;
  final double? width;
  final double? height;
  final BoxFit fit;
  final BorderRadius? borderRadius;

  const CmsImage({
    super.key,
    required this.kind,
    this.heroId,
    this.fallbackUrl,
    required this.fallbackText,
    this.width,
    this.height,
    this.fit = BoxFit.contain,
    this.borderRadius,
  });

  String? _cdnUrl() {
    if (heroId == null || heroId!.isEmpty) return null;
    if (kind == 'service') {
      return 'https://cdn.hero-sms.com/assets/img/service/${heroId}0.webp';
    } else if (kind == 'country') {
      return 'https://cdn.hero-sms.com/assets/img/country/${heroId}.svg';
    }
    return null;
  }

  String? _localUrl() {
    if (heroId == null || heroId!.isEmpty) return null;
    final base = AppConfig.apiBaseUrl;
    if (kind == 'service') {
      return '$base/pic/fuwu/${heroId}0.webp';
    } else if (kind == 'country') {
      return '$base/pic/country/${heroId}.svg';
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final cdn = _cdnUrl();
    final local = _localUrl();

    Widget placeholder = Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            Theme.of(context).colorScheme.primary.withOpacity(0.2),
            Theme.of(context).colorScheme.secondary.withOpacity(0.2),
          ],
        ),
        borderRadius: borderRadius ?? BorderRadius.circular(8),
      ),
      alignment: Alignment.center,
      child: Text(
        fallbackText.isNotEmpty ? fallbackText.substring(0, fallbackText.length > 2 ? 2 : fallbackText.length) : '?',
        style: TextStyle(
          fontSize: (height ?? 32) / 2.5,
          fontWeight: FontWeight.bold,
          color: Theme.of(context).colorScheme.primary,
        ),
      ),
    );

    if (fallbackUrl != null && fallbackUrl!.isNotEmpty) {
      return ClipRRect(
        borderRadius: borderRadius ?? BorderRadius.circular(8),
        child: CachedNetworkImage(
          imageUrl: fallbackUrl!,
          width: width,
          height: height,
          fit: fit,
          placeholder: (_, __) => placeholder,
          errorWidget: (_, __, ___) => placeholder,
        ),
      );
    }

    if (cdn == null) return placeholder;

    return ClipRRect(
      borderRadius: borderRadius ?? BorderRadius.circular(8),
      child: CachedNetworkImage(
        imageUrl: cdn,
        width: width,
        height: height,
        fit: fit,
        placeholder: (_, __) => placeholder,
        errorWidget: (_, url, error) {
          if (local != null) {
            return CachedNetworkImage(
              imageUrl: local,
              width: width,
              height: height,
              fit: fit,
              placeholder: (_, __) => placeholder,
              errorWidget: (_, __, ___) => placeholder,
            );
          }
          return placeholder;
        },
      ),
    );
  }
}
