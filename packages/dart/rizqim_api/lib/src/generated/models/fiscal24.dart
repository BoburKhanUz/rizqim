// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt5.dart';

part 'fiscal24.g.dart';

@JsonSerializable()
class Fiscal24 {
  const Fiscal24({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal24.fromJson(Map<String, Object?> json) =>
      _$Fiscal24FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt5? receipt;

  Map<String, Object?> toJson() => _$Fiscal24ToJson(this);
}
