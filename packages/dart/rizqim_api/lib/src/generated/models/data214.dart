// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account3.dart';
import 'entry2.dart';
import 'fiscal10.dart';

part 'data214.g.dart';

@JsonSerializable()
class Data214 {
  const Data214({
    required this.account,
    required this.entry,
    required this.fiscal,
  });

  factory Data214.fromJson(Map<String, Object?> json) =>
      _$Data214FromJson(json);

  final Account3 account;
  final Entry2 entry;
  final Fiscal10 fiscal;

  Map<String, Object?> toJson() => _$Data214ToJson(this);
}
