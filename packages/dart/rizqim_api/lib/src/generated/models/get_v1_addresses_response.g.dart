// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_addresses_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1AddressesResponse _$GetV1AddressesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1AddressesResponse(
  success: json['success'] as bool,
  data: (json['data'] as List<dynamic>)
      .map((e) => Address.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1AddressesResponseToJson(
  GetV1AddressesResponse instance,
) => <String, dynamic>{'success': instance.success, 'data': instance.data};
