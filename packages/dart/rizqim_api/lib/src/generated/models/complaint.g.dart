// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'complaint.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Complaint _$ComplaintFromJson(Map<String, dynamic> json) => Complaint(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  orderId: (json['order_id'] as num).toInt(),
  userId: (json['user_id'] as num).toInt(),
  type: json['type'] as String,
  description: json['description'] as String,
  status: json['status'] as String,
  reply: json['reply'] as String?,
  resolutionComment: json['resolution_comment'] as String?,
  resolvedBy: (json['resolved_by'] as num?)?.toInt(),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  supportStatus: ComplaintSupportStatus.fromJson(
    json['support_status'] as String,
  ),
  supportStatusChangedAt: json['support_status_changed_at'] == null
      ? null
      : DateTime.parse(json['support_status_changed_at'] as String),
  answeredAt: json['answered_at'] == null
      ? null
      : DateTime.parse(json['answered_at'] as String),
  resolvedAt: json['resolved_at'] == null
      ? null
      : DateTime.parse(json['resolved_at'] as String),
  order: json['order'] == null
      ? null
      : Order.fromJson(json['order'] as Map<String, dynamic>),
  user: json['user'] == null
      ? null
      : User.fromJson(json['user'] as Map<String, dynamic>),
  resolver: json['resolver'] == null
      ? null
      : User.fromJson(json['resolver'] as Map<String, dynamic>),
  assignee: json['assignee'] == null
      ? null
      : User.fromJson(json['assignee'] as Map<String, dynamic>),
  replies: (json['replies'] as List<dynamic>?)
      ?.map((e) => ComplaintReply.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$ComplaintToJson(Complaint instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'order_id': instance.orderId,
  'user_id': instance.userId,
  'type': instance.type,
  'description': instance.description,
  'status': instance.status,
  'reply': instance.reply,
  'resolution_comment': instance.resolutionComment,
  'resolved_by': instance.resolvedBy,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'support_status': instance.supportStatus,
  'support_status_changed_at': instance.supportStatusChangedAt
      ?.toIso8601String(),
  'answered_at': instance.answeredAt?.toIso8601String(),
  'resolved_at': instance.resolvedAt?.toIso8601String(),
  'order': instance.order,
  'user': instance.user,
  'resolver': instance.resolver,
  'assignee': instance.assignee,
  'replies': instance.replies,
};
