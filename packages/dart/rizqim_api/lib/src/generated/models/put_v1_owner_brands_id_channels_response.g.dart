// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_brands_id_channels_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerBrandsIdChannelsResponse _$PutV1OwnerBrandsIdChannelsResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerBrandsIdChannelsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'] as String,
);

Map<String, dynamic> _$PutV1OwnerBrandsIdChannelsResponseToJson(
  PutV1OwnerBrandsIdChannelsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
