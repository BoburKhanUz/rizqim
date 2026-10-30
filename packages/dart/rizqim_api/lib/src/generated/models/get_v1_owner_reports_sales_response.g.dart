// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_reports_sales_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerReportsSalesResponse _$GetV1OwnerReportsSalesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerReportsSalesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data146.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerReportsSalesResponseToJson(
  GetV1OwnerReportsSalesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
