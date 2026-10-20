// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data21.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data21 _$Data21FromJson(Map<String, dynamic> json) => Data21(
  items: (json['items'] as List<dynamic>)
      .map((e) => Items.fromJson(e as Map<String, dynamic>))
      .toList(),
  unreadCount: (json['unread_count'] as num).toInt(),
  pagination: Pagination.fromJson(json['pagination'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data21ToJson(Data21 instance) => <String, dynamic>{
  'items': instance.items,
  'unread_count': instance.unreadCount,
  'pagination': instance.pagination,
};
