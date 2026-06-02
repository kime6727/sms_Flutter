import '../core/config/app_config.dart';

class CountryModel {
  final int id;
  final String name;
  final String? nameEn;
  final String code;
  final String? flag;
  final String? phoneCode;
  final bool active;
  final String createdAt;
  final String updatedAt;

  CountryModel({
    required this.id,
    required this.name,
    this.nameEn,
    required this.code,
    this.flag,
    this.phoneCode,
    required this.active,
    required this.createdAt,
    required this.updatedAt,
  });

  factory CountryModel.fromJson(Map<String, dynamic> json) {
    return CountryModel(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      nameEn: json['name_en'],
      code: json['code'] ?? '',
      flag: json['flag'],
      phoneCode: json['phone_code'],
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
      'code': code,
      'flag': flag,
      'phone_code': phoneCode,
      'active': active,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }

  String get displayName => nameEn ?? name;

  String get localFlag {
    if (flag == null || flag!.isEmpty) return '';
    return flag!;
  }
}
