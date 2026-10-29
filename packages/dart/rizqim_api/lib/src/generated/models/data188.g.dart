// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data188.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data188 _$Data188FromJson(Map<String, dynamic> json) => Data188(
  channel: Channel6.fromJson(json['channel'] as String),
  menuVersion: json['menu_version'] as String,
  branch: Branch14.fromJson(json['branch'] as Map<String, dynamic>),
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

Map<String, dynamic> _$Data188ToJson(Data188 instance) => <String, dynamic>{
  'channel': instance.channel,
  'menu_version': instance.menuVersion,
  'branch': instance.branch,
  'categories': instance.categories,
  'products': instance.products,
  'availability': instance.availability,
};
