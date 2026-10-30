// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_reports_kitchen_times_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerReportsKitchenTimesResponse
_$GetV1OwnerReportsKitchenTimesResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerReportsKitchenTimesResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data145.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerReportsKitchenTimesResponseToJson(
  GetV1OwnerReportsKitchenTimesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
