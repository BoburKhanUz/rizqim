// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt.dart';

part 'fiscal20.g.dart';

@JsonSerializable()
class Fiscal20 {
  const Fiscal20({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal20.fromJson(Map<String, Object?> json) =>
      _$Fiscal20FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt? receipt;

  Map<String, Object?> toJson() => _$Fiscal20ToJson(this);
}
