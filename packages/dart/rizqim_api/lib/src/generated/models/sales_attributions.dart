// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sales_attributions.g.dart';

@JsonSerializable()
class SalesAttributions {
  const SalesAttributions({required this.value, required this.label});

  factory SalesAttributions.fromJson(Map<String, Object?> json) =>
      _$SalesAttributionsFromJson(json);

  final String value;
  final String label;

  Map<String, Object?> toJson() => _$SalesAttributionsToJson(this);
}
