// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt17.dart';

part 'fiscal36.g.dart';

@JsonSerializable()
class Fiscal36 {
  const Fiscal36({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal36.fromJson(Map<String, Object?> json) =>
      _$Fiscal36FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt17? receipt;

  Map<String, Object?> toJson() => _$Fiscal36ToJson(this);
}
