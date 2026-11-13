// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch20.dart';
import 'sale5.dart';
import 'buyer5.dart';
import 'fiscal24.dart';
import 'blocked6.dart';
import 'print5.dart';

part 'data299.g.dart';

@JsonSerializable()
class Data299 {
  const Data299({
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

  factory Data299.fromJson(Map<String, Object?> json) =>
      _$Data299FromJson(json);

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
  final Branch20? branch;
  final Sale5 sale;
  final Buyer5 buyer;
  final String? note;
  final Fiscal24 fiscal;
  final Blocked6? blocked;
  final Print5 print;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Data299ToJson(this);
}
