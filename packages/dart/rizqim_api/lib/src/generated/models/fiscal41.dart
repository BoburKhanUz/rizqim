// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'fiscal41.g.dart';

@JsonSerializable()
class Fiscal41 {
  const Fiscal41({required this.receiptUuid, required this.status});

  factory Fiscal41.fromJson(Map<String, Object?> json) =>
      _$Fiscal41FromJson(json);

  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? status;

  Map<String, Object?> toJson() => _$Fiscal41ToJson(this);
}
