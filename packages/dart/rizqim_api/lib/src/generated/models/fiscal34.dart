// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt15.dart';

part 'fiscal34.g.dart';

@JsonSerializable()
class Fiscal34 {
  const Fiscal34({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal34.fromJson(Map<String, Object?> json) =>
      _$Fiscal34FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt15? receipt;

  Map<String, Object?> toJson() => _$Fiscal34ToJson(this);
}
