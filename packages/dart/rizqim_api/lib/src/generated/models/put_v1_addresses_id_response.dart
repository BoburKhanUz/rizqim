// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'address.dart';

part 'put_v1_addresses_id_response.g.dart';

@JsonSerializable()
class PutV1AddressesIdResponse {
  const PutV1AddressesIdResponse({required this.success, required this.data});

  factory PutV1AddressesIdResponse.fromJson(Map<String, Object?> json) =>
      _$PutV1AddressesIdResponseFromJson(json);

  final bool success;
  final Address data;

  Map<String, Object?> toJson() => _$PutV1AddressesIdResponseToJson(this);
}
