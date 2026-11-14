// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt9.dart';

part 'fiscal28.g.dart';

@JsonSerializable()
class Fiscal28 {
  const Fiscal28({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal28.fromJson(Map<String, Object?> json) =>
      _$Fiscal28FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt9? receipt;

  Map<String, Object?> toJson() => _$Fiscal28ToJson(this);
}
