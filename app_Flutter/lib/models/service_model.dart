import '../core/config/app_config.dart';

class ServiceModel {
  final int id;
  final String name;
  final String? nameEn;
  final String? icon;
  final String? heroCode;
  final bool active;
  final String createdAt;
  final String updatedAt;

  ServiceModel({
    required this.id,
    required this.name,
    this.nameEn,
    this.icon,
    this.heroCode,
    required this.active,
    required this.createdAt,
    required this.updatedAt,
  });

  factory ServiceModel.fromJson(Map<String, dynamic> json) {
    return ServiceModel(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      nameEn: json['name_en'],
      icon: json['icon'],
      heroCode: json['hero_code'],
      active: json['active'] == 1 || json['active'] == true,
      createdAt: json['created_at'] ?? '',
      updatedAt: json['updated_at'] ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'name_en': nameEn,
      'icon': icon,
      'hero_code': heroCode,
      'active': active,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }

  String get displayName => nameEn ?? name;

  String get localIcon {
    if (icon == null || icon!.isEmpty) return '';
    return icon!;
  }
}
