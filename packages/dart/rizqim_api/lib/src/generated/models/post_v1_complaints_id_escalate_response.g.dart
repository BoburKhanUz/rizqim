// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_complaints_id_escalate_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1ComplaintsIdEscalateResponse _$PostV1ComplaintsIdEscalateResponseFromJson(
  Map<String, dynamic> json,
) => PostV1ComplaintsIdEscalateResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'] == null
      ? null
      : Complaint.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1ComplaintsIdEscalateResponseToJson(
  PostV1ComplaintsIdEscalateResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
