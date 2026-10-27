// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_superadmin_dashboard_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1SuperadminDashboardResponse _$GetV1SuperadminDashboardResponseFromJson(
  Map<String, dynamic> json,
) => GetV1SuperadminDashboardResponse(
  success: json['success'] as bool,
  data: Data191.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1SuperadminDashboardResponseToJson(
  GetV1SuperadminDashboardResponse instance,
) => <String, dynamic>{'success': instance.success, 'data': instance.data};
