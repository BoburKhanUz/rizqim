// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'store.dart';

part 'get_v1_stores_response.g.dart';

@JsonSerializable()
class GetV1StoresResponse {
  const GetV1StoresResponse({required this.success, required this.data});

  factory GetV1StoresResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1StoresResponseFromJson(json);

  final bool success;
  final List<Store> data;

  Map<String, Object?> toJson() => _$GetV1StoresResponseToJson(this);
}
