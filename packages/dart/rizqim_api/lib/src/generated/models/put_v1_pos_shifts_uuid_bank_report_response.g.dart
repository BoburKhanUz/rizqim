// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_pos_shifts_uuid_bank_report_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1PosShiftsUuidBankReportResponse
_$PutV1PosShiftsUuidBankReportResponseFromJson(Map<String, dynamic> json) =>
    PutV1PosShiftsUuidBankReportResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data200.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1PosShiftsUuidBankReportResponseToJson(
  PutV1PosShiftsUuidBankReportResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
