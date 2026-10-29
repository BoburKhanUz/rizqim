// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'complaint_reply.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ComplaintReply _$ComplaintReplyFromJson(Map<String, dynamic> json) =>
    ComplaintReply(
      id: (json['id'] as num).toInt(),
      complaintId: (json['complaint_id'] as num).toInt(),
      authorId: (json['author_id'] as num?)?.toInt(),
      body: json['body'] as String,
      cannedReplyId: (json['canned_reply_id'] as num?)?.toInt(),
      channel: json['channel'] as String,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
      complaint: json['complaint'] == null
          ? null
          : Complaint.fromJson(json['complaint'] as Map<String, dynamic>),
      author: json['author'] == null
          ? null
          : User.fromJson(json['author'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$ComplaintReplyToJson(ComplaintReply instance) =>
    <String, dynamic>{
      'id': instance.id,
      'complaint_id': instance.complaintId,
      'author_id': instance.authorId,
      'body': instance.body,
      'canned_reply_id': instance.cannedReplyId,
      'channel': instance.channel,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'complaint': instance.complaint,
      'author': instance.author,
    };
