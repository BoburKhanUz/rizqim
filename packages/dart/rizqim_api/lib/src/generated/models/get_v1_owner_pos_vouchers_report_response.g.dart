// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_vouchers_report_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosVouchersReportResponse
_$GetV1OwnerPosVouchersReportResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerPosVouchersReportResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data168.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerPosVouchersReportResponseToJson(
  GetV1OwnerPosVouchersReportResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
