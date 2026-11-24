// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data23.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data23 _$Data23FromJson(Map<String, dynamic> json) => Data23(
  items: (json['items'] as List<dynamic>)
      .map((e) => Items.fromJson(e as Map<String, dynamic>))
      .toList(),
  unreadCount: (json['unread_count'] as num).toInt(),
  pagination: Pagination.fromJson(json['pagination'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data23ToJson(Data23 instance) => <String, dynamic>{
  'items': instance.items,
  'unread_count': instance.unreadCount,
  'pagination': instance.pagination,
};
