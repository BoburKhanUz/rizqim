// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'delete_v1_addresses_id_response.g.dart';

@JsonSerializable()
class DeleteV1AddressesIdResponse {
  const DeleteV1AddressesIdResponse({
    required this.success,
    required this.message,
  });

  factory DeleteV1AddressesIdResponse.fromJson(Map<String, Object?> json) =>
      _$DeleteV1AddressesIdResponseFromJson(json);

  final bool success;
  final String message;

  Map<String, Object?> toJson() => _$DeleteV1AddressesIdResponseToJson(this);
}
