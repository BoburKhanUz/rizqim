// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data103.dart';

part 'get_v1_owner_fiscal_uzqr_response.g.dart';

@JsonSerializable()
class GetV1OwnerFiscalUzqrResponse {
  const GetV1OwnerFiscalUzqrResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerFiscalUzqrResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerFiscalUzqrResponseFromJson(json);

  final bool success;
  final String message;
  final Data103 data;

  Map<String, Object?> toJson() => _$GetV1OwnerFiscalUzqrResponseToJson(this);
}
