// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items _$ItemsFromJson(Map<String, dynamic> json) => Items(
  id: (json['id'] as num).toInt(),
  title: json['title'] as String,
  message: json['message'] as String,
  type: json['type'] as String,
  status: json['status'] as String,
  isRead: json['is_read'] as bool,
  readAt: json['read_at'] as String?,
  sentAt: json['sent_at'] as String,
);

Map<String, dynamic> _$ItemsToJson(Items instance) => <String, dynamic>{
  'id': instance.id,
  'title': instance.title,
  'message': instance.message,
  'type': instance.type,
  'status': instance.status,
  'is_read': instance.isRead,
  'read_at': instance.readAt,
  'sent_at': instance.sentAt,
};
