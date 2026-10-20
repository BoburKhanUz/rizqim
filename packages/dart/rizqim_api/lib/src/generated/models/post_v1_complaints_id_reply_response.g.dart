// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_complaints_id_reply_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1ComplaintsIdReplyResponse _$PostV1ComplaintsIdReplyResponseFromJson(
  Map<String, dynamic> json,
) => PostV1ComplaintsIdReplyResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'] == null
      ? null
      : Complaint.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1ComplaintsIdReplyResponseToJson(
  PostV1ComplaintsIdReplyResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
