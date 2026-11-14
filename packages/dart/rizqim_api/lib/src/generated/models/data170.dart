// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch11.dart';
import 'sale3.dart';
import 'buyer3.dart';
import 'fiscal6.dart';
import 'blocked4.dart';
import 'print3.dart';

part 'data170.g.dart';

@JsonSerializable()
class Data170 {
  const Data170({
    required this.uuid,
    required this.code,
    required this.kind,
    required this.faceValue,
    required this.balance,
    required this.currency,
    required this.validFrom,
    required this.validUntil,
    required this.status,
    required this.statusLabel,
    required this.branch,
    required this.sale,
    required this.buyer,
    required this.note,
    required this.fiscal,
    required this.blocked,
    required this.print,
    required this.createdAt,
  });

  factory Data170.fromJson(Map<String, Object?> json) =>
      _$Data170FromJson(json);

  final String uuid;
  final String code;
  final String kind;
  @JsonKey(name: 'face_value')
  final String faceValue;
  final String balance;
  final String currency;
  @JsonKey(name: 'valid_from')
  final String validFrom;
  @JsonKey(name: 'valid_until')
  final String? validUntil;
  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  final Branch11? branch;
  final Sale3 sale;
  final Buyer3 buyer;
  final String? note;
  final Fiscal6 fiscal;
  final Blocked4? blocked;
  final Print3? print;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Data170ToJson(this);
}
