// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data31.dart';

part 'post_v1_owner_devices_pairing_codes_response.g.dart';

@JsonSerializable()
class PostV1OwnerDevicesPairingCodesResponse {
  const PostV1OwnerDevicesPairingCodesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerDevicesPairingCodesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerDevicesPairingCodesResponseFromJson(json);

  final bool success;
  final String message;
  final Data31 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerDevicesPairingCodesResponseToJson(this);
}
