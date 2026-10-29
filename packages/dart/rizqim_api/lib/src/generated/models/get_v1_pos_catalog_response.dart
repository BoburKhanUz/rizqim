// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data159.dart';

part 'get_v1_pos_catalog_response.g.dart';

@JsonSerializable()
class GetV1PosCatalogResponse {
  const GetV1PosCatalogResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosCatalogResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosCatalogResponseFromJson(json);

  final bool success;
  final String message;
  final Data159 data;

  Map<String, Object?> toJson() => _$GetV1PosCatalogResponseToJson(this);
}
