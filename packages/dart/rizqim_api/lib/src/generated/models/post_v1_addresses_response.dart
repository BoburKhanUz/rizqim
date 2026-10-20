// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'address.dart';

part 'post_v1_addresses_response.g.dart';

@JsonSerializable()
class PostV1AddressesResponse {
  const PostV1AddressesResponse({required this.success, required this.data});

  factory PostV1AddressesResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1AddressesResponseFromJson(json);

  final bool success;
  final Address data;

  Map<String, Object?> toJson() => _$PostV1AddressesResponseToJson(this);
}
