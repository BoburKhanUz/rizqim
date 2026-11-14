// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt18.dart';

part 'fiscal37.g.dart';

@JsonSerializable()
class Fiscal37 {
  const Fiscal37({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal37.fromJson(Map<String, Object?> json) =>
      _$Fiscal37FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt18? receipt;

  Map<String, Object?> toJson() => _$Fiscal37ToJson(this);
}
