// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data7.g.dart';

@JsonSerializable()
class Data7 {
  const Data7({
    required this.id,
    required this.name,
    required this.phone,
    required this.telegramUsername,
    required this.branchId,
    required this.courierType,
  });

  factory Data7.fromJson(Map<String, Object?> json) => _$Data7FromJson(json);

  final int id;
  final String name;
  final String? phone;
  @JsonKey(name: 'telegram_username')
  final String? telegramUsername;
  @JsonKey(name: 'branch_id')
  final int? branchId;

  /// P1-24: the restaurant's own courier or the Rizqim fleet
  @JsonKey(name: 'courier_type')
  final String courierType;

  Map<String, Object?> toJson() => _$Data7ToJson(this);
}
