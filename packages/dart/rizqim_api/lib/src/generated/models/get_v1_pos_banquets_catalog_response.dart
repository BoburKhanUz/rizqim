// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data221.dart';

part 'get_v1_pos_banquets_catalog_response.g.dart';

@JsonSerializable()
class GetV1PosBanquetsCatalogResponse {
  const GetV1PosBanquetsCatalogResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosBanquetsCatalogResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosBanquetsCatalogResponseFromJson(json);

  final bool success;
  final String message;
  final Data221 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosBanquetsCatalogResponseToJson(this);
}
