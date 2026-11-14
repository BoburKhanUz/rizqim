// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_banquets_report_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosBanquetsReportResponse
_$GetV1OwnerPosBanquetsReportResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerPosBanquetsReportResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data146.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerPosBanquetsReportResponseToJson(
  GetV1OwnerPosBanquetsReportResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
