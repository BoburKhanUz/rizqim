// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_complaints_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1ComplaintsResponse _$GetV1ComplaintsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1ComplaintsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Complaint.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1ComplaintsResponseToJson(
  GetV1ComplaintsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
