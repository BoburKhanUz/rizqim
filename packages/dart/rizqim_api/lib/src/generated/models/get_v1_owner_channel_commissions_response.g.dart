// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_channel_commissions_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerChannelCommissionsResponse
_$GetV1OwnerChannelCommissionsResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerChannelCommissionsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data60.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerChannelCommissionsResponseToJson(
  GetV1OwnerChannelCommissionsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
