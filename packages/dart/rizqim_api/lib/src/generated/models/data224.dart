// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'accounts.dart';
import 'meta2.dart';

part 'data224.g.dart';

@JsonSerializable()
class Data224 {
  const Data224({required this.accounts, required this.meta});

  factory Data224.fromJson(Map<String, Object?> json) =>
      _$Data224FromJson(json);

  final List<Accounts> accounts;
  final Meta2 meta;

  Map<String, Object?> toJson() => _$Data224ToJson(this);
}
