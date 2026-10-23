// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt5.dart';

part 'fiscal6.g.dart';

@JsonSerializable()
class Fiscal6 {
  const Fiscal6({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal6.fromJson(Map<String, Object?> json) =>
      _$Fiscal6FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt5? receipt;

  Map<String, Object?> toJson() => _$Fiscal6ToJson(this);
}
