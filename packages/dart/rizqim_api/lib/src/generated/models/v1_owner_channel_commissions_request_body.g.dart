// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_channel_commissions_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerChannelCommissionsRequestBody
_$V1OwnerChannelCommissionsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerChannelCommissionsRequestBody(
      rates: (json['rates'] as List<dynamic>)
          .map((e) => Rates3.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$V1OwnerChannelCommissionsRequestBodyToJson(
  V1OwnerChannelCommissionsRequestBody instance,
) => <String, dynamic>{'rates': instance.rates};
