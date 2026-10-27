// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data22.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data22 _$Data22FromJson(Map<String, dynamic> json) => Data22(
  items: (json['items'] as List<dynamic>)
      .map((e) => Items.fromJson(e as Map<String, dynamic>))
      .toList(),
  unreadCount: (json['unread_count'] as num).toInt(),
  pagination: Pagination.fromJson(json['pagination'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data22ToJson(Data22 instance) => <String, dynamic>{
  'items': instance.items,
  'unread_count': instance.unreadCount,
  'pagination': instance.pagination,
};
