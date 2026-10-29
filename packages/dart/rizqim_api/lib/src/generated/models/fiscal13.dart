// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt11.dart';

part 'fiscal13.g.dart';

@JsonSerializable()
class Fiscal13 {
  const Fiscal13({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal13.fromJson(Map<String, Object?> json) =>
      _$Fiscal13FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt11? receipt;

  Map<String, Object?> toJson() => _$Fiscal13ToJson(this);
}
