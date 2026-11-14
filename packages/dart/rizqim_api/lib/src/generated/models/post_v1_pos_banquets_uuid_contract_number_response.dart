// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data229.dart';

part 'post_v1_pos_banquets_uuid_contract_number_response.g.dart';

@JsonSerializable()
class PostV1PosBanquetsUuidContractNumberResponse {
  const PostV1PosBanquetsUuidContractNumberResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBanquetsUuidContractNumberResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBanquetsUuidContractNumberResponseFromJson(json);

  final bool success;
  final String message;
  final Data229 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBanquetsUuidContractNumberResponseToJson(this);
}
