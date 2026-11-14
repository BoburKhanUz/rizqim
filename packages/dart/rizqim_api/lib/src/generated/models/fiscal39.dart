// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'fiscal39.g.dart';

@JsonSerializable()
class Fiscal39 {
  const Fiscal39({required this.receiptUuid, required this.status});

  factory Fiscal39.fromJson(Map<String, Object?> json) =>
      _$Fiscal39FromJson(json);

  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? status;

  Map<String, Object?> toJson() => _$Fiscal39ToJson(this);
}
