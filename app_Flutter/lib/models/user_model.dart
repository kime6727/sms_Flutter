class UserModel {
  final String id;
  final String username;
  final String email;
  final int points;
  /// 后端返回的是 decimal（DECIMAL(10,2)），这里用 double 保持精度
  final double totalSpent;
  final int orderCount;
  final bool hasTopupHistory;
  final String? firstTopupExpiresAt;
  /// 后端通过 /user/profile 返回的剩余倒计时小时数（非零即表示首充仍有效）
  final int firstTopupCountdownHours;
  final MembershipInfo? membership;
  final NextLevelInfo? nextLevel;
  final ProgressInfo? progress;
  final List<LevelInfo>? allLevels;
  final String createdAt;
  final String updatedAt;

  UserModel({
    required this.id,
    required this.username,
    required this.email,
    required this.points,
    this.totalSpent = 0.0,
    this.orderCount = 0,
    required this.hasTopupHistory,
    this.firstTopupExpiresAt,
    this.firstTopupCountdownHours = 0,
    this.membership,
    this.nextLevel,
    this.progress,
    this.allLevels,
    required this.createdAt,
    required this.updatedAt,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id']?.toString() ?? '',
      username: json['username']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      points: json['points'] ?? json['balance'] ?? 0,
      totalSpent: (json['total_spent'] is num)
          ? (json['total_spent'] as num).toDouble()
          : double.tryParse(json['total_spent']?.toString() ?? '0') ?? 0.0,
      orderCount: json['order_count'] ?? 0,
      hasTopupHistory: json['has_topup_history'] ?? false,
      firstTopupExpiresAt: json['first_topup_expires_at']?.toString(),
      firstTopupCountdownHours: (json['first_topup_countdown_hours'] ?? 0) is int
          ? json['first_topup_countdown_hours']
          : (json['first_topup_countdown_hours'] as num?)?.toInt() ?? 0,
      membership: json['membership'] != null ? MembershipInfo.fromJson(json['membership']) : null,
      nextLevel: json['next_level'] != null ? NextLevelInfo.fromJson(json['next_level']) : null,
      progress: json['progress'] != null ? ProgressInfo.fromJson(json['progress']) : null,
      allLevels: json['all_levels'] != null
          ? (json['all_levels'] as List).map((e) => LevelInfo.fromJson(e)).toList()
          : null,
      createdAt: json['created_at']?.toString() ?? '',
      updatedAt: json['updated_at']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'username': username,
      'email': email,
      'points': points,
      'total_spent': totalSpent,
      'order_count': orderCount,
      'has_topup_history': hasTopupHistory,
      'first_topup_expires_at': firstTopupExpiresAt,
      'membership': membership?.toJson(),
      'next_level': nextLevel?.toJson(),
      'progress': progress?.toJson(),
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }

  UserModel copyWith({
    String? id,
    String? username,
    String? email,
    int? points,
    double? totalSpent,
    int? orderCount,
    bool? hasTopupHistory,
    String? firstTopupExpiresAt,
    MembershipInfo? membership,
    NextLevelInfo? nextLevel,
    ProgressInfo? progress,
    List<LevelInfo>? allLevels,
    String? createdAt,
    String? updatedAt,
  }) {
    return UserModel(
      id: id ?? this.id,
      username: username ?? this.username,
      email: email ?? this.email,
      points: points ?? this.points,
      totalSpent: totalSpent ?? this.totalSpent,
      orderCount: orderCount ?? this.orderCount,
      hasTopupHistory: hasTopupHistory ?? this.hasTopupHistory,
      firstTopupExpiresAt: firstTopupExpiresAt ?? this.firstTopupExpiresAt,
      membership: membership ?? this.membership,
      nextLevel: nextLevel ?? this.nextLevel,
      progress: progress ?? this.progress,
      allLevels: allLevels ?? this.allLevels,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  bool get hasFirstTopupBonus {
    // 优先用后端返回的倒计时小时数（准确），兼容旧的 expires_at 字段
    if (firstTopupCountdownHours > 0) return true;
    if (firstTopupExpiresAt == null) return false;
    final expires = DateTime.tryParse(firstTopupExpiresAt!);
    return expires != null && expires.isAfter(DateTime.now());
  }

  String get membershipLabel {
    return membership?.levelCn ?? '普通用户';
  }

  int get membershipLevelIndex {
    if (membership == null) return 0;
    if (allLevels == null) return 0;
    return allLevels!.indexWhere((l) => l.name == membership!.level);
  }

  double get membershipProgress {
    return progress?.percentage ?? 0;
  }

  bool get isMaxLevel {
    return nextLevel == null;
  }
}

class MembershipInfo {
  final String level;
  final String levelCn;
  /// 后端返回的是 decimal（DECIMAL(10,2)），用 double 保持精度
  final double minSpent;
  final double discount;
  final String? icon;
  final String? color;

  MembershipInfo({
    required this.level,
    required this.levelCn,
    this.minSpent = 0.0,
    this.discount = 1.0,
    this.icon,
    this.color,
  });

  factory MembershipInfo.fromJson(Map<String, dynamic> json) {
    return MembershipInfo(
      level: json['level'] ?? '',
      levelCn: json['level_cn'] ?? '',
      minSpent: (json['min_spent'] is num)
          ? (json['min_spent'] as num).toDouble()
          : double.tryParse(json['min_spent']?.toString() ?? '0') ?? 0.0,
      discount: json['discount'] ?? 1.0,
      icon: json['icon'],
      color: json['color'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'level': level,
      'level_cn': levelCn,
      'min_spent': minSpent,
      'discount': discount,
      'icon': icon,
      'color': color,
    };
  }
}

class NextLevelInfo {
  final String level;
  final String levelCn;
  final double minSpent;
  final double discount;

  NextLevelInfo({
    required this.level,
    required this.levelCn,
    this.minSpent = 0.0,
    this.discount = 1.0,
  });

  factory NextLevelInfo.fromJson(Map<String, dynamic> json) {
    return NextLevelInfo(
      level: json['level'] ?? '',
      levelCn: json['level_cn'] ?? '',
      minSpent: (json['min_spent'] is num)
          ? (json['min_spent'] as num).toDouble()
          : double.tryParse(json['min_spent']?.toString() ?? '0') ?? 0.0,
      discount: json['discount'] ?? 1.0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'level': level,
      'level_cn': levelCn,
      'min_spent': minSpent,
      'discount': discount,
    };
  }
}

class ProgressInfo {
  final double current;
  final double needed;
  final double percentage;

  ProgressInfo({
    this.current = 0,
    this.needed = 0,
    this.percentage = 0,
  });

  factory ProgressInfo.fromJson(Map<String, dynamic> json) {
    return ProgressInfo(
      current: json['current'] ?? 0,
      needed: json['needed'] ?? 0,
      percentage: json['percentage'] ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'current': current,
      'needed': needed,
      'percentage': percentage,
    };
  }
}

class LevelInfo {
  final String name;
  final String nameCn;
  final double minSpent;
  final double discount;
  final String? icon;
  final String? color;

  LevelInfo({
    required this.name,
    required this.nameCn,
    this.minSpent = 0.0,
    this.discount = 1.0,
    this.icon,
    this.color,
  });

  factory LevelInfo.fromJson(Map<String, dynamic> json) {
    return LevelInfo(
      name: json['name'] ?? '',
      nameCn: json['name_cn'] ?? '',
      minSpent: (json['min_spent'] is num)
          ? (json['min_spent'] as num).toDouble()
          : double.tryParse(json['min_spent']?.toString() ?? '0') ?? 0.0,
      discount: json['discount'] ?? 1.0,
      icon: json['icon'],
      color: json['color'],
    );
  }
}
