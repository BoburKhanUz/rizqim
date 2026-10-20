// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_addresses_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AddressesResponse _$PostV1AddressesResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AddressesResponse(
  success: json['success'] as bool,
  data: Address.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AddressesResponseToJson(
  PostV1AddressesResponse instance,
) => <String, dynamic>{'success': instance.success, 'data': instance.data};
