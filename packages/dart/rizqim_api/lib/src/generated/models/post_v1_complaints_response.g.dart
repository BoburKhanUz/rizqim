// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_complaints_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1ComplaintsResponse _$PostV1ComplaintsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1ComplaintsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Complaint.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1ComplaintsResponseToJson(
  PostV1ComplaintsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
