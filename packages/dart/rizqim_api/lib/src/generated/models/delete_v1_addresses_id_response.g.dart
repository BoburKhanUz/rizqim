// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_addresses_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1AddressesIdResponse _$DeleteV1AddressesIdResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1AddressesIdResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
);

Map<String, dynamic> _$DeleteV1AddressesIdResponseToJson(
  DeleteV1AddressesIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
};
