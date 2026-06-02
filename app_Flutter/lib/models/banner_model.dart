class BannerModel {
  final int id;
  final String name;
  final String imageUrl;
  final String linkUrl;
  final int sortOrder;

  BannerModel({
    required this.id,
    required this.name,
    required this.imageUrl,
    required this.linkUrl,
    required this.sortOrder,
  });

  factory BannerModel.fromJson(Map<String, dynamic> json) {
    return BannerModel(
      id: json['id'] as int,
      name: json['name'] as String,
      imageUrl: json['image_url'] as String,
      linkUrl: json['link_url'] as String,
      sortOrder: json['sort_order'] as int? ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'image_url': imageUrl,
      'link_url': linkUrl,
      'sort_order': sortOrder,
    };
  }
}
