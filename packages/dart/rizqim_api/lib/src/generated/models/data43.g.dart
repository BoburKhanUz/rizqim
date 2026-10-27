// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data43.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data43 _$Data43FromJson(Map<String, dynamic> json) => Data43(
  items: (json['items'] as List<dynamic>)
      .map((e) => Items.fromJson(e as Map<String, dynamic>))
      .toList(),
  unreadCount: (json['unread_count'] as num).toInt(),
  pagination: Pagination.fromJson(json['pagination'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data43ToJson(Data43 instance) => <String, dynamic>{
  'items': instance.items,
  'unread_count': instance.unreadCount,
  'pagination': instance.pagination,
};
