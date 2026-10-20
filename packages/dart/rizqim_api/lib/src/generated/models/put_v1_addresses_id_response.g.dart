// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_addresses_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1AddressesIdResponse _$PutV1AddressesIdResponseFromJson(
  Map<String, dynamic> json,
) => PutV1AddressesIdResponse(
  success: json['success'] as bool,
  data: Address.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1AddressesIdResponseToJson(
  PutV1AddressesIdResponse instance,
) => <String, dynamic>{'success': instance.success, 'data': instance.data};
