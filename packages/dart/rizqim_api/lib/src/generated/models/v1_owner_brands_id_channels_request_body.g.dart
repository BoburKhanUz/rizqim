// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_brands_id_channels_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBrandsIdChannelsRequestBody _$V1OwnerBrandsIdChannelsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerBrandsIdChannelsRequestBody(
  channels: (json['channels'] as List<dynamic>)
      .map((e) => Channels3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$V1OwnerBrandsIdChannelsRequestBodyToJson(
  V1OwnerBrandsIdChannelsRequestBody instance,
) => <String, dynamic>{'channels': instance.channels};
