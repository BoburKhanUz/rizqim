// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt11.dart';

part 'fiscal30.g.dart';

@JsonSerializable()
class Fiscal30 {
  const Fiscal30({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal30.fromJson(Map<String, Object?> json) =>
      _$Fiscal30FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt11? receipt;

  Map<String, Object?> toJson() => _$Fiscal30ToJson(this);
}
