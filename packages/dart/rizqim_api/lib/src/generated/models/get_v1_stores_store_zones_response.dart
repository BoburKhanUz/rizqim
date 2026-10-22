// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data178.dart';

part 'get_v1_stores_store_zones_response.g.dart';

@JsonSerializable()
class GetV1StoresStoreZonesResponse {
  const GetV1StoresStoreZonesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1StoresStoreZonesResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1StoresStoreZonesResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data178> data;

  Map<String, Object?> toJson() => _$GetV1StoresStoreZonesResponseToJson(this);
}
