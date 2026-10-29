// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt.dart';

part 'fiscal3.g.dart';

@JsonSerializable()
class Fiscal3 {
  const Fiscal3({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal3.fromJson(Map<String, Object?> json) =>
      _$Fiscal3FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt? receipt;

  Map<String, Object?> toJson() => _$Fiscal3ToJson(this);
}
