// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt3.dart';

part 'fiscal22.g.dart';

@JsonSerializable()
class Fiscal22 {
  const Fiscal22({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal22.fromJson(Map<String, Object?> json) =>
      _$Fiscal22FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt3? receipt;

  Map<String, Object?> toJson() => _$Fiscal22ToJson(this);
}
