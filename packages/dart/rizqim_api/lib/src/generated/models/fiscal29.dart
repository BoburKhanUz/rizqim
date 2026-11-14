// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt10.dart';

part 'fiscal29.g.dart';

@JsonSerializable()
class Fiscal29 {
  const Fiscal29({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal29.fromJson(Map<String, Object?> json) =>
      _$Fiscal29FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt10? receipt;

  Map<String, Object?> toJson() => _$Fiscal29ToJson(this);
}
