// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt3.dart';

part 'fiscal5.g.dart';

@JsonSerializable()
class Fiscal5 {
  const Fiscal5({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal5.fromJson(Map<String, Object?> json) =>
      _$Fiscal5FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt3? receipt;

  Map<String, Object?> toJson() => _$Fiscal5ToJson(this);
}
