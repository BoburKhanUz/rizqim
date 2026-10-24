// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data139.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data139 _$Data139FromJson(Map<String, dynamic> json) => Data139(
  channel: Channel6.fromJson(json['channel'] as String),
  menuVersion: json['menu_version'] as String,
  branch: Branch12.fromJson(json['branch'] as Map<String, dynamic>),
  categories: (json['categories'] as List<dynamic>)
      .map((e) => Category.fromJson(e as Map<String, dynamic>))
      .toList(),
  products: (json['products'] as List<dynamic>)
      .map((e) => Products.fromJson(e as Map<String, dynamic>))
      .toList(),
  availability: Availability.fromJson(
    json['availability'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$Data139ToJson(Data139 instance) => <String, dynamic>{
  'channel': instance.channel,
  'menu_version': instance.menuVersion,
  'branch': instance.branch,
  'categories': instance.categories,
  'products': instance.products,
  'availability': instance.availability,
};
