// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt13.dart';

part 'fiscal15.g.dart';

@JsonSerializable()
class Fiscal15 {
  const Fiscal15({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal15.fromJson(Map<String, Object?> json) =>
      _$Fiscal15FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt13? receipt;

  Map<String, Object?> toJson() => _$Fiscal15ToJson(this);
}
