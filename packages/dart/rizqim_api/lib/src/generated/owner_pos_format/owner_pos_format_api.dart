// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_pos_format_response.dart';
import '../models/post_v1_owner_pos_format_template_response.dart';
import '../models/put_v1_owner_pos_format_response.dart';
import '../models/v1_owner_pos_format_request_body.dart';
import '../models/v1_owner_pos_format_template_request_body.dart';

part 'owner_pos_format_api.g.dart';

@RestApi()
abstract class OwnerPosFormatApi {
  factory OwnerPosFormatApi(Dio dio, {String? baseUrl}) = _OwnerPosFormatApi;

  /// The branch's format.
  ///
  /// `profile` (one of 14: choyxona, osh_markazi, lagmonxona, somsaxona, tamaddixona, stolovaya, kafe, restoran,.
  /// kabobxona, baliqxona, fast_food, qahvaxona, toyxona, dark_kitchen), `mode` (counter|table|hybrid), `first_screen`.
  /// (hall_map|quick_counter|booking_calendar|online_orders), `modules`: the effective modules (as GET /pos/bootstrap.
  /// `format.modules`), `locks.service_charge_zero` (stolovaya). `module_rows`: one per module — `default` (the.
  /// profile's), `override` (the owner's, null: none), `configured` (default or override, minus the law), `plan_allows`.
  /// with `required_plan` when not, `locked` (law: cannot be switched on; plan: the plan lacks it; null) and `enabled`.
  /// (the result). `profiles`: the picker (each profile's mode, first screen, modules, stations, number of sample.
  /// dishes). `template`: the branch's profile's sample stations and dishes, each with `exists` (applying skips it).
  ///
  /// [branchId] - The branch (a branch-bound caller: their own when omitted).
  @GET('/v1/owner/pos/format')
  Future<GetV1OwnerPosFormatResponse> posFormatShow({
    @Query('branch_id') int? branchId,
  });

  /// Change the branch's format.
  ///
  /// Send what changes. `profile`: a new profile starts from its own mode and modules (the earlier switches are.
  /// dropped; send `modules` with it to keep some). `mode`: counter|table|hybrid (a new profile's own when left out).
  /// `modules`: {module: true|false|null} — true/false switches a module on or off over the profile's default, null goes.
  /// back to the default; an unknown module is 422. Switching on a module the plan lacks: 403 plan_feature_missing;.
  /// the service charge of a stolovaya: 422 service_charge_locked. A locked subscription changes nothing (403.
  /// subscription_locked). Audited (settings.pos_format_changed, the profile change named).
  @PUT('/v1/owner/pos/format')
  Future<PutV1OwnerPosFormatResponse> posFormatUpdate({
    @Body() V1OwnerPosFormatRequestBody? body,
  });

  /// Apply the sample stations and menu.
  ///
  /// The profile's (`profile`, else the branch's) sample kitchen stations on the branch (`stations`, default true) and.
  /// sample dishes in the store's menu (`menu`, default true; needs menu.manage too). Nothing existing is changed: a.
  /// station of the same name (or the same osh/mangal/salat va bar/choy kind) and a product of the same name are.
  /// skipped; dishes are added switched off with a 0 price (set the price, then switch them on). Running it again.
  /// adds nothing. A locked subscription: 403 subscription_locked. Audited (settings.pos_template_applied).
  @POST('/v1/owner/pos/format/template')
  Future<PostV1OwnerPosFormatTemplateResponse> posFormatApplyTemplate({
    @Body() V1OwnerPosFormatTemplateRequestBody? body,
  });
}
