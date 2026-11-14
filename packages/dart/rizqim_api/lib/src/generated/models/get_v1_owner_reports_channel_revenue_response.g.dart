// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_reports_channel_revenue_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerReportsChannelRevenueResponse
_$GetV1OwnerReportsChannelRevenueResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerReportsChannelRevenueResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: json['data'] as String,
    );

Map<String, dynamic> _$GetV1OwnerReportsChannelRevenueResponseToJson(
  GetV1OwnerReportsChannelRevenueResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
